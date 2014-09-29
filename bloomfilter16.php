<?php
/* 
Copyright (c) 2012, Da Xue
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
1. Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.
3. The name of the author nor the names of its contributors may be used 
   to endorse or promote products derived from this software without 
   specific prior written permission.

THIS SOFTWARE IS PROVIDED BY DA XUE ''AS IS'' AND ANY
EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL DA XUE BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

/* https://github.com/dsx724/php-bloom-filter */

#This is the optimized version using MD5 only.

class BloomFilter16 {
	private static function merge($bf1,$bf2,$bfout,$union = false){
		if ($bf1->m != $bf2->m) throw new Exception('Unable to merge due to vector difference.');
		if ($bf1->k != $bf2->k) throw new Exception('Unable to merge due to hash count difference.');
		if ($union){
			for ($i = 0; $i < strlen($bfout->bit_array); $i++) $bfout->bit_array[$i] = chr(ord($bf1->bit_array[$i]) | ord($bf2->bit_array[$i]));
			$bfout->n = $bf1->n + $bf2->n;
		} else {
			for ($i = 0; $i < strlen($bfout->bit_array); $i++) $bfout->bit_array[$i] = chr(ord($bf1->bit_array[$i]) & ord($bf2->bit_array[$i]));
			$bfout->n = abs($bf1->n - $bf2->n);
		}
	}
	public static function createFromProbability($n, $p){
		if ($p <= 0 || $p >= 1) throw new Exception('Invalid false positive rate requested.');
		if ($n <= 0) throw new Exception('Invalid capacity requested.');
		$k = floor(log(1/$p,2));
		$m = pow(2,ceil(log(-$n*log($p)/pow(log(2),2),2))); //approximate estimator method
		return new self($m,$k);
	}
	public static function getUnion($bf1,$bf2){
		$bf = new self($bf1->m,$bf1->k,$bf1->hash);
		self::merge($bf1,$bf2,$bf,true);
		return $bf;
	}
	public static function getIntersection($bf1,$bf2){
		$bf = new self($bf1->m,$bf1->k,$bf1->hash);
		self::merge($bf1,$bf2,$bf,false);
		return $bf;
	}
	private $n = 0; // # of entries
	private $m; // # of bits in array
	private $k; // # of hash functions
	private $mask;
	private $hash_size;
	private $bit_array; // data structure
	public function __construct($m, $k){
		if ($m < 8) throw new Exception('The bit array length must be at least 8 bits.');
		if ($m & ($m - 1) == 0) throw new Exception('The bit array length must be power of 2.');
		if ($m > 65536) throw new Exception('The maximum data structure size is 8KB.');
		if ($k > 8) throw new Exception('The maximum bits to set is 8.');
		$this->m = $m;
		$this->k = $k;
		$this->k2 = $k * 2;
		$address_bits = (int)log($m,2);
		$this->mask = (1 << $address_bits) - 8;
		$this->bit_array = (binary)(str_repeat("\0",$this->getArraySize(true)));
	}
	public function calculateProbability($n = 0){
		return pow(1-pow(1-1/$this->m,$this->k*($n ?: $this->n)),$this->k);
		// return pow(1-exp($this->k*($n ?: $this->n)/$this->m),$this->k); //approximate estimator
	}
	public function calculateCapacity($p){
		return floor($this->m*log(2)/log($p,1-pow(1-1/$this->m,$this->m*log(2))));
	}
	public function getElementCount(){
		return $this->n;
	}
	public function getArraySize($bytes = false){
		return $this->m >> ($bytes ? 3 : 0);
	}
	public function getHashCount(){
		return $this->k;
	}
	public function getInfo($p = null){
		$units = array('','K','M','G','T','P','E','Z','Y');
		$M = $this->getArraySize(true);
		$magnitude = intval(floor(log($M,1024)));
		$unit = $units[$magnitude];
		$M /= pow(1024,$magnitude);
		return 'Allocated '.$this->getArraySize().' bits ('.$M.' '.$unit.'Bytes)'.PHP_EOL.
			'Using '.$this->getHashCount(). ' (16b) hashes'.PHP_EOL.
			'Contains '.$this->getElementCount().' elements'.PHP_EOL.
			(isset($p) ? 'Capacity of '.number_format($this->calculateCapacity($p)).' (p='.$p.')'.PHP_EOL : '');
	}
	public function add($key){
		$hash = md5($key,true);
		for ($index = 0; $index < $this->k2; $index++){
			$hash_sub = (ord($hash[$index++]) << 8) | ord($hash[$index]);
			$word = ($hash_sub & $this->mask) >> 3;
			$this->bit_array[$word] = chr(ord($this->bit_array[$word]) | 1 << ($hash_sub & 7));
		}
		$this->n++;
	}
	public function contains($key){
		$hash = md5($key,true);
		for ($index = 0; $index < $this->k2; $index++){
			$hash_sub = (ord($hash[$index++]) << 8) | ord($hash[$index]);
			if ((ord($this->bit_array[($hash_sub & $this->mask) >> 3]) & (1 << ($hash_sub & 7))) === 0) return false;
		}
		return true;
	}
	public function unionWith($bf){
		self::merge($this,$bf,$this,true);
	}
	public function intersectWith($bf){
		self::merge($this,$bf,$this,false);
	}
}
?>