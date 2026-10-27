// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data44.g.dart';

@JsonSerializable()
class Data44 {
  const Data44({
    required this.orderUuid,
    required this.orderNumber,
    required this.status,
    required this.paymentUrl,
  });

  factory Data44.fromJson(Map<String, Object?> json) => _$Data44FromJson(json);

  @JsonKey(name: 'order_uuid')
  final String orderUuid;
  @JsonKey(name: 'order_number')
  final String? orderNumber;
  final String status;
  @JsonKey(name: 'payment_url')
  final dynamic paymentUrl;

  Map<String, Object?> toJson() => _$Data44ToJson(this);
}
