// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items2.dart';
import 'channel2.dart';
import 'type3.dart';
import 'payment_method.dart';

part 'v1_orders_request_body.g.dart';

@JsonSerializable()
class V1OrdersRequestBody {
  const V1OrdersRequestBody({
    required this.storeId,
    required this.items,
    required this.type,
    required this.paymentMethod,
    this.channel,
    this.branchId,
    this.address,
    this.latitude,
    this.longitude,
    this.notes,
    this.promoCode,
  });

  factory V1OrdersRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OrdersRequestBodyFromJson(json);

  @JsonKey(name: 'store_id')
  final int storeId;
  final List<Items2> items;
  final Channel2? channel;
  final Type3 type;
  @JsonKey(name: 'payment_method')
  final PaymentMethod paymentMethod;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final String? address;
  final num? latitude;
  final num? longitude;
  final String? notes;
  @JsonKey(name: 'promo_code')
  final String? promoCode;

  Map<String, Object?> toJson() => _$V1OrdersRequestBodyToJson(this);
}
