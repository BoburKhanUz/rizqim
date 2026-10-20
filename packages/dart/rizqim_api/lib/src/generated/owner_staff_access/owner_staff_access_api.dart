// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_staff_id_pin_response.dart';
import '../models/get_v1_owner_permissions_response.dart';
import '../models/put_v1_owner_permissions_response.dart';
import '../models/put_v1_owner_staff_id_pin_response.dart';
import '../models/put_v1_owner_staff_id_role_response.dart';
import '../models/v1_owner_permissions_request_body.dart';
import '../models/v1_owner_staff_id_pin_request_body.dart';
import '../models/v1_owner_staff_id_role_request_body.dart';

part 'owner_staff_access_api.g.dart';

@RestApi()
abstract class OwnerStaffAccessApi {
  factory OwnerStaffAccessApi(Dio dio, {String? baseUrl}) =
      _OwnerStaffAccessApi;

  /// GET /owner/permissions: the store's role -> permission matrix, with the defaults alongside
  @GET('/v1/owner/permissions')
  Future<GetV1OwnerPermissionsResponse> staffAccessPermissions();

  /// PUT /owner/permissions {role, permission, allowed: true|false|null}: grant, take away, or back to the default
  @PUT('/v1/owner/permissions')
  Future<PutV1OwnerPermissionsResponse> staffAccessUpdatePermission({
    @Body() required V1OwnerPermissionsRequestBody body,
  });

  /// PUT /owner/staff/{id}/pin {pin}
  @PUT('/v1/owner/staff/{id}/pin')
  Future<PutV1OwnerStaffIdPinResponse> staffAccessSetPin({
    @Path('id') required int id,
    @Body() required V1OwnerStaffIdPinRequestBody body,
  });

  /// DELETE /owner/staff/{id}/pin: the person can no longer sign in on terminals until a new PIN is set
  @DELETE('/v1/owner/staff/{id}/pin')
  Future<DeleteV1OwnerStaffIdPinResponse> staffAccessResetPin({
    @Path('id') required int id,
  });

  /// PUT /owner/staff/{id}/role {role, branch_id?}: owner only (staff.manage); never the owner's own account
  @PUT('/v1/owner/staff/{id}/role')
  Future<PutV1OwnerStaffIdRoleResponse> staffAccessUpdateRole({
    @Path('id') required int id,
    @Body() required V1OwnerStaffIdRoleRequestBody body,
  });
}
