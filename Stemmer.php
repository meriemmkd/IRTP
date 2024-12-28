<?php
class Stemmer {
    /**
     * Apply Porter Stemming Algorithm to a word.
     *
     * @param string $word The input word to stem.
     * @return string The stemmed word.
     */
    public function stem($word) {
        $word = strtolower($word); // Convert to lowercase
        if (strlen($word) <= 2) {
            return $word; // Ignore short words
        }

        // Step 1a
        if (substr($word, -1) === 's') {
            if (substr($word, -4) === 'sses') {
                $word = substr($word, 0, -2);
            } elseif (substr($word, -3) === 'ies') {
                $word = substr($word, 0, -2);
            } elseif (substr($word, -2) !== 'ss') {
                $word = substr($word, 0, -1);
            }
        }

        // Step 1b
        if (substr($word, -3) === 'eed') {
            if ($this->measure(substr($word, 0, -3)) > 0) {
                $word = substr($word, 0, -1);
            }
        } elseif (
            (substr($word, -2) === 'ed' && $this->containsVowel(substr($word, 0, -2))) ||
            (substr($word, -3) === 'ing' && $this->containsVowel(substr($word, 0, -3)))
        ) {
            $word = substr($word, 0, -3);
            if (substr($word, -2) === 'at' || substr($word, -2) === 'bl' || substr($word, -2) === 'iz') {
                $word .= 'e';
            } elseif ($this->endsWithDoubleConsonant($word) && substr($word, -1) !== 'l' && substr($word, -1) !== 's' && substr($word, -1) !== 'z') {
                $word = substr($word, 0, -1);
            } elseif ($this->measure($word) === 1 && $this->endsWithCVC($word)) {
                $word .= 'e';
            }
        }

        // Step 1c
        if (substr($word, -1) === 'y' && $this->containsVowel(substr($word, 0, -1))) {
            $word = substr($word, 0, -1) . 'i';
        }

        return $word;
    }

    /**
     * Measure the number of VC sequences in the word.
     *
     * @param string $word
     * @return int
     */
    private function measure($word) {
        return preg_match_all('/[aeiou]+[^aeiou]+/', $word);
    }

    /**
     * Check if the word contains a vowel.
     *
     * @param string $word
     * @return bool
     */
    private function containsVowel($word) {
        return preg_match('/[aeiou]/', $word);
    }

    /**
     * Check if the word ends with a double consonant.
     *
     * @param string $word
     * @return bool
     */
    private function endsWithDoubleConsonant($word) {
        return preg_match('/([^aeiou])\1$/', $word);
    }

    /**
     * Check if the word ends with CVC.
     *
     * @param string $word
     * @return bool
     */
    private function endsWithCVC($word) {
        return preg_match('/[^aeiou][aeiou][^aeiou]$/', $word) && !preg_match('/[wxy]$/', $word);
    }
}
?>
