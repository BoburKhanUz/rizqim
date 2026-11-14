// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_channel_commissions_response.dart';
import '../models/get_v1_owner_reports_channel_revenue_response.dart';
import '../models/put_v1_owner_channel_commissions_response.dart';
import '../models/v1_owner_channel_commissions_request_body.dart';

part 'owner_channel_revenue_api.g.dart';

@RestApi()
abstract class OwnerChannelRevenueApi {
  factory OwnerChannelRevenueApi(Dio dio, {String? baseUrl}) =
      _OwnerChannelRevenueApi;

  /// Channel commissions.
  ///
  /// The owner's commission percent per channel ({channel, percent, note}); a channel without one uses the order's.
  /// own RIZQIM commission (docs/BILLING.md). `channels`: the channels a rate may name.
  @GET('/v1/owner/channel-commissions')
  Future<GetV1OwnerChannelCommissionsResponse> channelRevenueCommissions();

  /// Replace the channel commissions.
  ///
  /// `rates`: [{channel, percent (0–100, two decimals), note}]; a channel left out goes back to the order's own.
  /// commission. Audited.
  @PUT('/v1/owner/channel-commissions')
  Future<PutV1OwnerChannelCommissionsResponse> channelRevenueUpdateCommissions({
    @Body() required V1OwnerChannelCommissionsRequestBody body,
  });

  /// Channel net revenue.
  ///
  /// `rows`: one per channel × brand ({channel, channel_label, commission_percent, commission_source: owner|billing,.
  /// brand_id (null: the house brand), brand_name, brand_code, brand_color, orders, gross, discounts, refunds,.
  /// commission, net}); `by_channel`, `by_brand`, `totals`. Money as decimal strings in so'm. The period is at most.
  /// 366 days (store time zone days; default today).
  ///
  /// [from] - First day (YYYY-MM-DD).
  ///
  /// [to] - Last day (YYYY-MM-DD).
  ///
  /// [branchId] - One branch (a branch-bound caller: their own).
  @GET('/v1/owner/reports/channel-revenue')
  Future<GetV1OwnerReportsChannelRevenueResponse> channelRevenueShow({
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('branch_id') int? branchId,
  });

  /// Channel net revenue (Excel).
  ///
  /// The same report as an .xlsx workbook: "Kanal va brend", "Kanallar", "Brendlar". Recorded in the audit log.
  ///
  /// [from] - First day (YYYY-MM-DD).
  ///
  /// [to] - Last day (YYYY-MM-DD).
  ///
  /// [branchId] - One branch (a branch-bound caller: their own).
  @GET('/v1/owner/reports/channel-revenue/export')
  @DioResponseType(ResponseType.stream)
  Stream<String> channelRevenueExport({
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('branch_id') int? branchId,
  });
}
