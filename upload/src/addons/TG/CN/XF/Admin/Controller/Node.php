<?php 

namespace TG\CN\XF\Admin\Controller;

use XF\Mvc\ParameterBag;

class Node extends XFCP_Node
{
    public function actionClone(ParameterBag $params)
    {
        $node = $this->assertNodeExists($params->node_id);
        
        if (!$this->isPost())
        {
            $nodeRepo = $this->getNodeRepo();
            $nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList(null, 'NodeType'));
            
            $viewParams = [
                'node' => $node,
                'nodeTree' => $nodeTree
            ];
            
            return $this->view(null, 'tgcn_clone', $viewParams);
        }
        
        $input = $this->filter([
            'title' => 'str',
            'parent_node_id' => 'int',
            'display_in_list' => 'bool',
            
            'child_clone' => 'bool',
        ]);
        
        $newNode = $this->cloneNode($node, [
            'title' => $input['title'],
            'parent_node_id' => $input['parent_node_id'],
            'display_in_list' => $input['display_in_list']
        ]);
     
        if ($input['child_clone'])
        {
            $this->cloneChildNode($node, $newNode);
        }
        
        return $this->redirect($this->buildLink('nodes/edit', $newNode));
    }
    
    protected function cloneChildNode(\XF\Entity\Node $node, \XF\Entity\Node $newNode)
    {
        $childNodes = $this->finder('XF:Node')
                ->where('parent_node_id', $node->node_id)
                ->fetch();
                
        foreach ($childNodes as $child)
        {
            $newChild = $this->cloneNode($child, [
                'parent_node_id' => $newNode->node_id
            ]);
            
            $this->cloneChildNode($child, $newChild);
        }
    }
    
    protected function cloneNode(\XF\Entity\Node $node, $newValues = [])
    {
        $form = $this->formAction();
        
        $values = array_merge($node->toArray(), $newValues);
        unset($values['node_id'], $values['node_name']);
        
        /** Fix: [EWR] Discord **/
        if (array_search('discord_options', array_keys($values)) !== false)
        {
            $values['discord_options'] = [];
        }
        /** ------------------ **/
        
        $node = \XF::em()->create('XF:Node');
        $node->bulkSet($values);
        
        $data = $node->getDataRelationOrDefault();
        $node->addCascadedSave($data);
        
        $form->basicEntitySave($node, $values);
        $form->run();
        
        return $node;
    }
}