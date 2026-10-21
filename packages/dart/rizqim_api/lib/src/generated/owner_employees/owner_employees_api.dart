// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_employees_id_response.dart';
import '../models/get_v1_owner_employees_response.dart';
import '../models/post_v1_owner_employees_employee_id_transfer_response.dart';
import '../models/post_v1_owner_employees_invite_response.dart';
import '../models/v1_owner_employees_employee_id_transfer_request_body.dart';
import '../models/v1_owner_employees_invite_request_body.dart';

part 'owner_employees_api.g.dart';

@RestApi()
abstract class OwnerEmployeesApi {
  factory OwnerEmployeesApi(Dio dio, {String? baseUrl}) = _OwnerEmployeesApi;

  /// Get list of employees (managers, cashiers, waiters, cooks, couriers) for the current store.
  ///
  /// The store's staff as users, newest first, with their branch and, for one who has not joined yet, the invite.
  /// token of their link.
  @GET('/v1/owner/employees')
  Future<GetV1OwnerEmployeesResponse> ownerEmployeeIndex();

  /// Generate an invite link for a new employee
  @POST('/v1/owner/employees/invite')
  Future<PostV1OwnerEmployeesInviteResponse> ownerEmployeeGenerateInvite({
    @Body() required V1OwnerEmployeesInviteRequestBody body,
  });

  /// Transfer an employee to another branch
  @POST('/v1/owner/employees/{employeeId}/transfer')
  Future<PostV1OwnerEmployeesEmployeeIdTransferResponse>
  ownerEmployeeUpdateBranch({
    @Path('employeeId') required String employeeId,
    @Body() required V1OwnerEmployeesEmployeeIdTransferRequestBody body,
  });

  /// Delete an employee
  @DELETE('/v1/owner/employees/{id}')
  Future<DeleteV1OwnerEmployeesIdResponse> ownerEmployeeDestroy({
    @Path('id') required String id,
  });
}
