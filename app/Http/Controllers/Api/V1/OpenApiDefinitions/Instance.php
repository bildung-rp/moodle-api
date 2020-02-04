<?php

/**
* @OA\Get(
*      path="/v1/instances",
*      operationId="instances",
*      tags={"Instance v1"},
*      summary="Get all instances",
*      description="Returns a collection of instances objects",
*      security={
*           {"passport": {"*"}},
*      },
*      @OA\Response(
*          response=201,
*          description="successful operation",
*          @OA\Schema(ref="#/components/schemas/Instances"),
*       ),
*       @OA\Response(response=409, description="Bad Request"),
* )
*
*/

/**
* @OA\POST(
*      path="/v1/instances",
*      operationId="createInstance",
*      tags={"Instance v1"},
*      summary="Create new instance",
*      description="Returns a the new instance object",
*      security={
*           {"passport": {"*"}},
*      },
*      @OA\RequestBody(
*       required=true,
*       @OA\MediaType(
*           mediaType="application/x-www-form-urlencoded",
*           @OA\Schema(
*               type="object",
*               required={"CN", "rpIdmOrgShortName"},
*               @OA\Property(
*                   property="CN",
*                   description="Instance Org and Number",
*                   type="string"
*               ),
*               @OA\Property(
*                   property="rpIdmOrgShortName",
*                   description="Common Name for this Instance",
*                   type="string"
*               ),
*           )
*       )
*   ),
*      
*      @OA\Response(
*          response=201,
*          description="successful operation",
*          @OA\Schema(ref="#/components/schemas/Instances"),
*       ),
*       @OA\Response(response=409, description="Bad request"),
*      
* )
*/

/**
* @OA\Delete(
*      path="/v1/instances/{CN}",
*      operationId="deleteInstance",
*      tags={"Instance v1"},
*      summary="Delete instance by CN",
*      description="Delete a instance object",
*      security={
*           {"passport": {"*"}},
*      },
*      @OA\Parameter(
*          name="CN",
*          description="Instance CN",
*          required=true,
*          in="path",
*          @OA\Schema(
*              type="string"
*          )
*      ),
*      
*      @OA\Response(
*          response=201,
*          description="successful operation",   
*       ),
*       @OA\Response(response=409, description="Bad request"),
*      
* )
* 
*/
