// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_branch_variants_response.dart';
import '../models/post_v1_owner_branch_variants_response.dart';
import '../models/v1_owner_branch_variants_request_body.dart';

part 'owner_branch_availability_api.g.dart';

@RestApi()
abstract class OwnerBranchAvailabilityApi {
  factory OwnerBranchAvailabilityApi(Dio dio, {String? baseUrl}) =
      _OwnerBranchAvailabilityApi;

  /// List all products and variants with their branch override settings.
  ///
  /// A manager always gets their own branch; an owner must send `branch_id` (422 without it).
  ///
  /// [branchId] - Owner: the branch to list (required). Ignored for a manager.
  @GET('/v1/owner/branch-variants')
  Future<GetV1OwnerBranchVariantsResponse> branchProductVariantIndex({
    @Query('branch_id') int? branchId,
  });

  /// Update or Create branch-specific overrides for a variant
  @POST('/v1/owner/branch-variants')
  Future<PostV1OwnerBranchVariantsResponse> branchProductVariantUpdate({
    @Body() required V1OwnerBranchVariantsRequestBody body,
  });
}
