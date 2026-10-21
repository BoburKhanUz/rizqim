// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_reports_sales_response.dart';
import '../models/get_v1_owner_reports_top_products_response.dart';

part 'owner_reports_api.g.dart';

@RestApi()
abstract class OwnerReportsApi {
  factory OwnerReportsApi(Dio dio, {String? baseUrl}) = _OwnerReportsApi;

  /// Download a report as CSV.
  ///
  /// `report`: `orders` (one row per order, at most 93 days and 50 000 orders), `sales` (the `group_by` series and a.
  /// total row, at most 366 days) or `products` (the top 50, `sort` as in top-products). Streamed as.
  /// `text/csv; charset=UTF-8` with a BOM; money in UZS with two decimals; times in the store's timezone.
  /// Too many orders for one file: 422, pick a shorter period. Rate limited (`report-csv`).
  ///
  /// [report] - orders | sales | products.
  ///
  /// [from] - Birinchi kun (YYYY-MM-DD).
  ///
  /// [to] - Oxirgi kun (YYYY-MM-DD).
  ///
  /// [branchId] - Filial.
  ///
  /// [groupBy] - sales uchun: day | week | month.
  ///
  /// [sort] - products uchun: quantity | revenue.
  @GET('/v1/owner/reports/export')
  @DioResponseType(ResponseType.stream)
  Stream<String> reportExport({
    @Query('report') String? report,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('branch_id') int? branchId,
    @Query('group_by') String? groupBy,
    @Query('sort') String? sort,
  });

  /// Sales for a period.
  ///
  /// `from` and `to` (YYYY-MM-DD, both included, at most 366 days) are calendar days of the store's timezone; an.
  /// order counts on the day it was placed. `revenue` is the total of the delivered orders. `series` has one row per.
  /// `group_by` bucket (day; week from Monday; month), zeros included. `branch_id` narrows to a branch (a branch-bound.
  /// manager always gets theirs). Money is a string with two decimals.
  ///
  /// [from] - Birinchi kun (YYYY-MM-DD).
  ///
  /// [to] - Oxirgi kun (YYYY-MM-DD), ko'pi bilan 366 kun.
  ///
  /// [branchId] - Filial.
  ///
  /// [groupBy] - day | week | month (standart day).
  @GET('/v1/owner/reports/sales')
  Future<GetV1OwnerReportsSalesResponse> reportSales({
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('branch_id') int? branchId,
    @Query('group_by') String? groupBy,
  });

  /// Best sellers for a period.
  ///
  /// Among the delivered orders, by the name the item had when sold: `sort` quantity (default) or revenue, `limit`.
  /// 1–50 (default 10). `quantity` is a whole number for pieces and a decimal for kg / l.
  ///
  /// [from] - Birinchi kun (YYYY-MM-DD).
  ///
  /// [to] - Oxirgi kun (YYYY-MM-DD), ko'pi bilan 366 kun.
  ///
  /// [branchId] - Filial.
  ///
  /// [limit] - 1–50, standart 10.
  ///
  /// [sort] - quantity | revenue.
  @GET('/v1/owner/reports/top-products')
  Future<GetV1OwnerReportsTopProductsResponse> reportTopProducts({
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('branch_id') int? branchId,
    @Query('limit') int? limit,
    @Query('sort') String? sort,
  });
}
