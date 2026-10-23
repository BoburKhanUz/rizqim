// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_printers_id_response.dart';
import '../models/get_v1_owner_printers_response.dart';
import '../models/patch_v1_owner_printers_id_response.dart';
import '../models/post_v1_owner_printers_response.dart';
import '../models/v1_owner_printers_id_request_body.dart';
import '../models/v1_owner_printers_request_body.dart';

part 'owner_printers_api.g.dart';

@RestApi()
abstract class OwnerPrintersApi {
  factory OwnerPrintersApi(Dio dio, {String? baseUrl}) = _OwnerPrintersApi;

  /// The branch's printers.
  ///
  /// By `branch_id` (a branch-bound caller: their own); without it, every branch of the store. `device_id` is the.
  /// terminal's public uuid (a USB or built-in printer belongs to one terminal).
  ///
  /// [branchId] - Only this branch.
  @GET('/v1/owner/printers')
  Future<GetV1OwnerPrintersResponse> printerIndex({
    @Query('branch_id') int? branchId,
  });

  /// Add a printer.
  ///
  /// `purpose`: receipt, kitchen or label. `connection`: lan (needs `address`, `port` defaults to 9100), bluetooth.
  /// (needs `address`), usb or builtin (need `device_id`: the uuid of the POS terminal it is attached to; no.
  /// address). `paper_width`: 58 or 80 mm. `station_id` (a station of the same branch) only for kitchen and label.
  /// printers. `encoding`: the code page when the printer's fonts need one; `print_as_image`: print the text as a.
  /// picture. `name` is unique within the branch.
  @POST('/v1/owner/printers')
  Future<PostV1OwnerPrintersResponse> printerStore({
    @Body() required V1OwnerPrintersRequestBody body,
  });

  /// Delete a printer
  @DELETE('/v1/owner/printers/{id}')
  Future<DeleteV1OwnerPrintersIdResponse> printerDestroy({
    @Path('id') required int id,
  });

  /// Edit a printer.
  ///
  /// Any subset of the create fields (the branch cannot change). The connection rules apply to the result:.
  /// switching to usb or builtin needs `device_id`, to lan an `address`.
  @PATCH('/v1/owner/printers/{id}')
  Future<PatchV1OwnerPrintersIdResponse> printerUpdate({
    @Path('id') required int id,
    @Body() V1OwnerPrintersIdRequestBody? body,
  });
}
