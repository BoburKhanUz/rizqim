// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_branches_id_response.dart';
import '../models/get_v1_owner_branches_id_response.dart';
import '../models/get_v1_owner_branches_response.dart';
import '../models/patch_v1_owner_branches_id_response.dart';
import '../models/post_v1_owner_branches_response.dart';
import '../models/v1_owner_branches_id_request_body.dart';
import '../models/v1_owner_branches_request_body.dart';

part 'owner_branches_api.g.dart';

@RestApi()
abstract class OwnerBranchesApi {
  factory OwnerBranchesApi(Dio dio, {String? baseUrl}) = _OwnerBranchesApi;

  /// The store's branches.
  ///
  /// Oldest first. A branch-bound caller gets their own branch only. `working_hours` is one daily window in the.
  /// store's timezone (`all_day`: no hours set; `overnight`: closes after midnight); `is_open_now` is what delivery.
  /// checkout sees now (status, active flag and hours).
  @GET('/v1/owner/branches')
  Future<GetV1OwnerBranchesResponse> branchIndex();

  /// Create a branch.
  ///
  /// Owner only (settings.manage). Required as in the panel: `name`, `address`, `region_id`, `district_id` (of that.
  /// region), `latitude`, `longitude`. `open_time` and `close_time` ("HH:MM", both or neither): one daily window;.
  /// equal times or none = round the clock, closing before opening = overnight (e.g. 18:00–02:00).
  @POST('/v1/owner/branches')
  Future<PostV1OwnerBranchesResponse> branchStore({
    @Body() required V1OwnerBranchesRequestBody body,
  });

  /// One branch
  @GET('/v1/owner/branches/{id}')
  Future<GetV1OwnerBranchesIdResponse> branchShow({
    @Path('id') required int id,
  });

  /// Delete a branch.
  ///
  /// Owner only (settings.manage). Refused (422) for a branch that has orders, or the store's only branch: make it.
  /// inactive instead.
  @DELETE('/v1/owner/branches/{id}')
  Future<DeleteV1OwnerBranchesIdResponse> branchDestroy({
    @Path('id') required int id,
  });

  /// Update a branch.
  ///
  /// Any subset of the create fields, plus `status` (active, inactive, temporarily_closed) and `is_active`.
  /// branches.manage: a branch-bound manager edits their own branch only (403 for another one).
  @PATCH('/v1/owner/branches/{id}')
  Future<PatchV1OwnerBranchesIdResponse> branchUpdate({
    @Path('id') required int id,
    @Body() V1OwnerBranchesIdRequestBody? body,
  });
}
