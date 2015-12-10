<?php

#
#  ParseCronSchedule.class.php
#
#  Copyright 2012 - 2014, Jonathon Wardman. All rights reserved.
#  Contact: jonathon@flutt.co.uk / flutt.co.uk
#
#  This program is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Lesser General Public License as published by
#  the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU Lesser General Public License for more details.
#
#  You should have received a copy of the GNU Lesser General Public License
#  along with this program.  If not, see <http://www.gnu.org/licenses/>.
#

/**
 *
 * Parses cron schedule segments and can return information relating to when
 * cron jobs should be run.
 *
 */
class ParseCronSchedule
{

    /* Magic methods. */

    public function __construct()
    {

    }

    public function __destruct()
    {

    }

    /* Public methods. */

    /**
     *
     * Checks if a specific cron schedule line would mean the job should be run
     * at this, or a defined, time. If no time to test against is set then the
     * current time will be used for the comparison.
     *
     * @param string $cronExpression Cron schedule expression (eg. '1 * * * *').
     * @param int $testTimestamp Unix timestamp to check the expression against. (Optional.)
     * @return boolean True if the cron would run at the specified time, false if not.
     * @throws \Exception
     */
    public function shouldRun($cronExpression, $testTimestamp = null)
    {

        // Explode the cron expression, and check it's got enough parts...
        $expressionElements = preg_split('/\s+/', $cronExpression);

        if ((count($expressionElements) < 5) || (count($expressionElements) > 6)) {
            throw new Exception('Invalid cron expression: must contain 4 or 5 elements');
        }

        // Set up an array with the time we are checking against...
        if ($testTimestamp == null) {
            $testTimestamp = time();
        }
        
        $cronFireTime = [
           'parseMinute' => [(int)date('i', $testTimestamp), $expressionElements[0]],
           'parseHour' => [(int)date('G', $testTimestamp), $expressionElements[1]],
           'parseDoM' => [(int)date('j', $testTimestamp), $expressionElements[2]],
           'parseMonth' => [(int)date('n', $testTimestamp), $expressionElements[3]],
           'parseDoW' => [(int)date('w', $testTimestamp), $expressionElements[4]],
           'parseYear' => [(int)date('Y', $testTimestamp), (isset($expressionElements[5]) ? $expressionElements[5] : date('Y', $testTimestamp))],
        ];

        // Now work through the parts of the expression and check if they are valid
        // for the time we are checking against. We'll return false immediatly if
        // we find a no-match, and throw an exception if the element isn't valid...
        foreach ($cronFireTime AS $checkMethod => $validValue) {
            $expandedExpressionElement = $this->$checkMethod($validValue[1]);
            if ($expandedExpressionElement !== false && !in_array($validValue[0], $expandedExpressionElement)) {
                return false;
            } else {
                throw new Exception('Invalid cron expression: invalid segment for ' . $checkMethod);
            }
        }
        // If we get to here without returning or throwing an exception then this
        // expression must be true for the check time so return true...
        return true;
    }

    /**
     *
     * Parses the minute section of a crontab line (the first field).
     *
     * @param string $minute String representation of the minute element of a cron expression.
     * @return array A full array of the minutes which are represented by the $minute expression.
     *
     */
    public function parseMinute($minute)
    {

        // Minute can only contain standard characters between 0 and 59...
        return $this->_parseStandardCharacters($minute, 0, 59);

    }

    /**
     *
     * Parses the hour section of a crontab line (the second field).
     *
     * @param string $hour String representation of the hour element of a cron expression.
     * @return array A full array of the hours which are represented by the $hour expression.
     *
     */
    public function parseHour($hour)
    {

        // Hours can only contain standard characters betwen 0 and 23...
        return $this->_parseStandardCharacters($hour, 0, 23);

    }

    /**
     *
     * Parses the day of month of a crontab line (the third field).
     *
     * @param string $dayOfMonth String representation of the DoM element of a cron expression.
     * @return array A full array of the DoMs which are represented by the $dayOfMonth expression.
     *
     */
    public function parseDoM($dayOfMonth)
    {

        // DoM can only contain standard characters betwen 1 and 31...
        return $this->_parseStandardCharacters($dayOfMonth, 1, 31);

    }

    /**
     *
     * Parses the month of a crontab line (the fourth field).
     *
     * @param string $month String representation of the month element of a cron expression.
     * @return array A full array of the months which are represented by the $month expression.
     *
     */
    public function parseMonth($month)
    {

        // DoM can only contain standard characters betwen 1 and 12...
        return $this->_parseStandardCharacters($month, 1, 12);

    }

    /**
     *
     * Parses the day of week of a crontab line (the fith field).
     *
     * @param string $dayOfWeek String representation of the day of week element of a cron expression.
     * @return array A full array of the DoWs which are represented by the $dayOfWeek expression.
     *
     */
    public function parseDoW($dayOfWeek)
    {

        // DoW can only contain standard characters betwen 0 and 6...
        return $this->_parseStandardCharacters($dayOfWeek, 0, 6);

    }

    /**
     *
     * Parses the year of a crontab line (the sixth field).
     *
     * @param string $year String representation of the year element of a cron expression.
     * @return array A full array of the years which are represented by the $year expression.
     *
     */
    public function parseYear($year)
    {

        // DoW can only contain standard characters betwen 0 and 6...
        return $this->_parseStandardCharacters($year, 1970, 2099);

    }

    /* Private and protected methods. */

    protected function _parseStandardCharacters($characterString, $lowerLimit, $upperLimit)
    {

        // Check the limits we have been passed...
        if (isset($lowerLimit) && is_numeric($lowerLimit) && isset($upperLimit) && is_numeric($upperLimit)) {
            if ($upperLimit > $lowerLimit) {

                // Prepare the final array of numbers...
                $numberArray = array();

                // As wildcard is a special case, check for that first...
                if ($characterString == '*') {
                    for ($i = $lowerLimit; $i <= $upperLimit; $i++) {
                        $numberArray[] = $i;
                    }

                    // If it's not * then parse what it actually is...
                } else {

                    // It could simply be one number...
                    if (is_numeric($characterString)) {
                        $numberArray = array($characterString);

                        // Or it might be a comma separated list of numbers which we can explode...
                    } else {
                        if (strpos($characterString, ',')) {
                            $numberArray = explode(',', $characterString);

                            // Or it might be more complicated than that...
                        } else {

                            // If it looks like an increment style set of numbers...
                            if (preg_match('/[\*\d,-]+\/[\d,-]+/', $characterString)) {
                                $characterChunks = explode('/', $characterString);
                                $range = $this->_parseStandardCharacters($characterChunks[0], $lowerLimit, $upperLimit);
                                $numberArray = $this->_rangeIncrement($range, $characterChunks[1]);

                                // Finally, if this is a range it's quite easy to deal with...
                            } else {
                                if (preg_match('/\d{1,2}-\d{1,2}/', $characterString)) {
                                    $characterChunks = explode('-', $characterString);
                                    if (($characterChunks[0] >= $lowerLimit) && ($characterChunks[0] <= $upperLimit) && ($characterChunks[1] >= $lowerLimit) && ($characterChunks[1] <= $upperLimit)) {
                                        for ($i = intval($characterChunks[0]); $i <= intval($characterChunks[1]); $i++) {
                                            $numberArray[] = $i;
                                        }
                                    }
                                }
                            }

                        }
                    }

                }

                // Now, it's possible that this array needs further parsing, so let's deal with that...
                $sanitisedNumberArray = array();
                foreach ($numberArray AS $numberValue) {
                    if (!is_numeric($numberValue)) {
                        $functionName = __FUNCTION__;
                        $sanitisedNumberArray = array_merge($sanitisedNumberArray, $this->$functionName($numberValue, $lowerLimit, $upperLimit));
                    } else {
                        if (($numberValue >= $lowerLimit) && ($numberValue <= $upperLimit)) {
                            $sanitisedNumberArray[] = intval($numberValue);
                        }
                    }
                }

                // And finally dedupe and sort the array before returning it...
                $sanitisedNumberArray = array_values(array_unique($sanitisedNumberArray));
                sort($sanitisedNumberArray, SORT_NUMERIC);
                return $sanitisedNumberArray;

            } else {
                return false;
            }
        } else {
            return false;
        }

    }

    /**
     *
     * Returns all the values in the $range array which are valid for the given
     * value of $increment. The lowest numeric value of $rage is used as the
     * first entry in the returned array. For example, if $range holds numbers
     * from 3 to 59 and $increment is 15, the returned array will contain 3, 18,
     * 33 and 48.
     *
     * @param array $range Range to extract values from.
     * @param int $increment Frequency to select numbers from range.
     * @return array Values from $range which are valid based on $increment.
     *
     */
    private function _rangeIncrement(array $range, $increment)
    {

        // Prepare the array to return...
        $returnArray = array();

        // Then sort and find the lowest value in the range...
        $rangeBase = min($range);

        // Before looping through the full range and adding the required values to the return...
        foreach ($range AS $rangeItem) {
            $itemDifference = $rangeItem - $rangeBase;
            if (($itemDifference % $increment) == 0) {
                $returnArray[] = $rangeItem;
            }
        }

        // Sort and return the result...
        sort($returnArray, SORT_NUMERIC);
        return $returnArray;

    }

}
