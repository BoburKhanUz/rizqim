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

  /// The store's permission matrix.
  ///
  /// GET /owner/permissions (permissions.manage; not from a terminal PIN session). `roles`: owner (`editable: false`,.
  /// its set is fixed), then manager, cashier, waiter, cook, courier (`editable: true`), each with its Uzbek `label`.
  /// `permissions`: every permission `key` (e.g. `orders.discount`, `pos.reprint_bill`, `pos.cash_in_out`) with its.
  /// Uzbek `label`; `grantable: false` ones (orders.refund, finance.manage, settings.manage, zones.manage,.
  /// staff.manage, audit.view, permissions.manage) stay the owner's and can only be taken away from a role, never.
  /// granted. `matrix`: this store's effective set, role => permission key => allowed (every role and every key.
  /// present). `defaults`: the same shape for the role defaults (RolePermissions; docs/AUTH.md): a cell that differs.
  /// from it is this store's override. 403 `store_required` for an account without a store.
  @GET('/v1/owner/permissions')
  Future<GetV1OwnerPermissionsResponse> staffAccessPermissions();

  /// Change one cell of the matrix.
  ///
  /// PUT /owner/permissions {role, permission, allowed} (permissions.manage). `role`: manager, cashier, waiter, cook or.
  /// courier (the owner's set is fixed: 422). `permission`: a permission key. `allowed`: true grants it to the role in.
  /// this store, false takes it away, null puts the role's default back (sending the default itself is the same as.
  /// null). Granting a non-grantable permission: 422. Audited (`permissions.changed`) when the cell changes; takes.
  /// effect at once for every staff member of that role, terminal PIN sessions included. Answers the cell as it is.
  /// now (`allowed`: the effective value).
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
