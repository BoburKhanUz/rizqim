// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data175.dart';

part 'get_v1_pos_orders_uuid_bills_response.g.dart';

@JsonSerializable()
class GetV1PosOrdersUuidBillsResponse {
  const GetV1PosOrdersUuidBillsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosOrdersUuidBillsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosOrdersUuidBillsResponseFromJson(json);

  final bool success;
  final String message;
  final Data175 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosOrdersUuidBillsResponseToJson(this);
}
