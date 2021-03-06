<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class PhabricatorFeedStreamController extends PhabricatorFeedController {

  public function processRequest() {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    if ($request->isFormPost()) {
      $story = id(new PhabricatorFeedStoryPublisher())
        ->setRelatedPHIDs(array($viewer->getPHID()))
        ->setStoryType(PhabricatorFeedStoryTypeConstants::STORY_STATUS)
        ->setStoryTime(time())
        ->setStoryAuthorPHID($viewer->getPHID())
        ->setStoryData(
          array(
            'content' => $request->getStr('status')
          ))
        ->publish();

      return id(new AphrontRedirectResponse())->setURI(
        $request->getRequestURI());
    }

    $query = new PhabricatorFeedQuery();
    $stories = $query->execute();

    $handles = array();
    $objects = array();
    if ($stories) {
      $handle_phids = array_mergev(mpull($stories, 'getRequiredHandlePHIDs'));
      $object_phids = array_mergev(mpull($stories, 'getRequiredObjectPHIDs'));

      $handles = id(new PhabricatorObjectHandleData($handle_phids))
        ->loadHandles();

      $objects = id(new PhabricatorObjectHandleData($object_phids))
        ->loadObjects();
    }

    $views = array();
    foreach ($stories as $story) {
      $story->setHandles($handles);
      $story->setObjects($objects);

      $view = $story->renderView();
      $view->setViewer($viewer);

      $views[] = $view->render();
    }

    $post_form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Pithy Wit')
          ->setName('status'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Publish'));

    $post = new AphrontPanelView();
    $post->setWidth(AphrontPanelView::WIDTH_FORM);
    $post->setHeader('High Horse Soapbox');
    $post->appendChild($post_form);

    $page = array();
    $page[] = $post;
    $page[] = $views;

    return $this->buildStandardPageResponse(
      $page,
      array(
        'title' => 'Feed',
      ));
  }
}
