// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data153.dart';

part 'post_v1_pos_orders_uuid_charges_charge_waive_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidChargesChargeWaiveResponse {
  const PostV1PosOrdersUuidChargesChargeWaiveResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidChargesChargeWaiveResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidChargesChargeWaiveResponseFromJson(json);

  final bool success;
  final String message;
  final Data153 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidChargesChargeWaiveResponseToJson(this);
}
