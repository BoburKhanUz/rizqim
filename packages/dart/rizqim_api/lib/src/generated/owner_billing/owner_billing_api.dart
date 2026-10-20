// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_invoices_response.dart';
import '../models/get_v1_owner_subscription_response.dart';
import '../models/subscription_invoice_status.dart';

part 'owner_billing_api.g.dart';

@RestApi()
abstract class OwnerBillingApi {
  factory OwnerBillingApi(Dio dio, {String? baseUrl}) = _OwnerBillingApi;

  /// The store's subscription invoices, newest month first
  @GET('/v1/owner/invoices')
  Future<GetV1OwnerInvoicesResponse> ownerBillingInvoices({
    @Query('status') SubscriptionInvoiceStatus? status,
    @Query('period') String? period,
    @Query('per_page') int? perPage,
  });

  /// The current plan and period, the terminals counted so far and an estimate of the period's invoice.
  /// `subscription` is null for a store on no plan (commission at the store's own rate, no monthly fee).
  @GET('/v1/owner/subscription')
  Future<GetV1OwnerSubscriptionResponse> ownerBillingSubscription();
}
