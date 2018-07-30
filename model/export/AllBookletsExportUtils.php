<?php
/**
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA.
 */

namespace oat\taoResultExports\model\export;

use qtism\common\datatypes\QtiDuration;

class AllBookletsExportUtils
{
    static public function getInteractions(\stdClass $json)
    {
        $interactions = array(
            'associateInteraction',
            'choiceInteraction',
            'drawingInteraction',
            'extendedTextInteraction',
            'gapMatchInteraction',
            'graphicAssociateInteraction',
            'graphicGapMatchInteraction',
            'graphicOrderInteraction',
            'hotspotInteraction',
            'selectPointInteraction',
            'hottextInteraction',
            'matchInteraction',
            'mediaInteraction',
            'orderInteraction',
            'sliderInteraction',
            'uploadInteraction',
            'endAttemptInteraction',
            'inlineChoiceInteraction',
            'textEntryInteraction',
            'positionObjectInteraction',
            'customInteraction'
        );
        
        $result = array();

        $stack = new \SplStack();
        $stack->push($json);
        
        while (count($stack) > 0) {
            
            $current = $stack->pop();
            
            if (isset($current->qtiClass) && isset($current->attributes) && isset($current->attributes->responseIdentifier) && in_array($current->qtiClass, $interactions)) {
                
                if (!isset($result[$current->qtiClass])) {
                    $result[$current->qtiClass] = array();
                }
                
                $result[$current->qtiClass][$current->attributes->responseIdentifier] = $current;
            }
            
            if ($current instanceof \stdClass) {
                foreach ($current as $val) {
                    $stack->push($val);
                }
            }
        }
        
        return $result;
    }
    
    static public function getMatchSets(\stdClass $jsonInteraction, $responseIdentifier)
    {
        $result = [];
        $choices = [];
        
        if (isset($jsonInteraction->qtiClass)) {
            if ($jsonInteraction->qtiClass === 'matchInteraction') {
                for ($i = 0; $i <= 1; $i++) {
                    $choices = [];
                    foreach ($jsonInteraction->choices[$i] as $choice) {
                        $choices[] = $choice->identifier;
                    }
                    $result[] = $choices;
                }
            } elseif ($jsonInteraction->qtiClass === 'gapMatchInteraction') {
                if (isset($jsonInteraction->body) && isset($jsonInteraction->body->elements)) {
                    $choices = [];
                    foreach ($jsonInteraction->body->elements as $element) {
                        if ($element->qtiClass === 'gap') {
                            $choices[] = $element->attributes->identifier;
                        }
                    }
                    $result[] = $choices;
                    
                    $choices = [];
                    
                    if (isset($jsonInteraction->choices)) {
                        foreach ($jsonInteraction->choices as $choice) {
                            $choices[] = $choice->attributes->identifier;
                        }
                    }
                    $result[] = $choices;
                }
            } elseif ($jsonInteraction->qtiClass === 'hottextInteraction') {
                if (isset($jsonInteraction->body) && isset($jsonInteraction->body->elements)) {
                    $choices = [];
                    foreach ($jsonInteraction->body->elements as $element) {
                        if ($element->qtiClass === 'hottext') {
                            $choices[] = $element->attributes->identifier;
                        }
                    }
                    $result[] = $choices;
                }
            } elseif ($jsonInteraction->qtiClass === 'choiceInteraction' || $jsonInteraction->qtiClass === 'inlineChoiceInteraction' || $jsonInteraction->qtiClass = 'orderInteraction') {

                foreach ($jsonInteraction->choices as $choice) {
                    $choices[] = $choice->identifier;
                }
                $result[] = $choices;
            }
        }
        
        return $result;
    }
    
    static public function isMatchByColumn(\stdClass $jsonInteraction)
    {
        $result = false;

        if (isset($jsonInteraction->qtiClass) && $jsonInteraction->qtiClass === 'matchInteraction') {
            
            foreach ($jsonInteraction->choices[0] as $choice) {
                if ($choice->attributes->matchMax != 1) {
                    break;
                }
                
                $result = true;
            }
        }
        
        return $result;
    }
    
    static public function isMatchByRow(\stdClass $jsonInteraction)
    {
        $result = false;
        
        if (isset($jsonInteraction->qtiClass) && $jsonInteraction->qtiClass === 'matchInteraction') {
            foreach ($jsonInteraction->choices[1] as $choice) {
                if ($choice->attributes->matchMax != 1) {
                    break;
                }
                
                $result = true;
            }
        }
        
        return $result;
    }
    
    static public function formatDuration($duration)
    {
        try {
            $qtiDuration = new QtiDuration($duration);
            $seconds = $qtiDuration->getSeconds(true);
            $microseconds = $qtiDuration->getMicroseconds();
            $strMicroseconds = strval($microseconds);
            
            $offset = 6 - strlen($strMicroseconds);
            for ($i = 0; $i < $offset; $i++) {
                $strMicroseconds = '0' . $strMicroseconds;
            }
            
            $floatDuration = floatval("${seconds}.${strMicroseconds}");
            return strval(round($floatDuration, 3));
            
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
    
    static public function matchSetIndex($identifier, array $matchSets)
    {
        $index = false;
        
        foreach ($matchSets as $matchSet) {
            if (($search = array_search($identifier, $matchSet, true)) !== false) {
                $index = $search;
                break;
            }
        }
        
        reset($matchSets);
        
        return $index;
    }
}
