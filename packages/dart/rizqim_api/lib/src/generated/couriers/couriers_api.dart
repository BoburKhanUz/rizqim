// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_couriers_response.dart';

part 'couriers_api.g.dart';

@RestApi()
abstract class CouriersApi {
  factory CouriersApi(Dio dio, {String? baseUrl}) = _CouriersApi;

  /// Couriers an order can be assigned to (orders.assign_courier).
  ///
  /// The store's couriers (and staff who deliver), with a label for their role. P2-30: by the orders.assign_courier.
  /// permission (owner and manager by default), not the role; staff tied to a branch get their branch's couriers.
  ///
  /// [branchId] - Owner only: that branch's couriers plus those of no branch. A manager always gets their own branch.
  @GET('/v1/couriers')
  Future<GetV1CouriersResponse> orderCouriers({
    @Query('branch_id') int? branchId,
  });
}
