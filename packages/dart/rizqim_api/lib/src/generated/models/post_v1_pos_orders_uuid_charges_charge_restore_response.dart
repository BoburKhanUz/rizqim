// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data213.dart';

part 'post_v1_pos_orders_uuid_charges_charge_restore_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidChargesChargeRestoreResponse {
  const PostV1PosOrdersUuidChargesChargeRestoreResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidChargesChargeRestoreResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidChargesChargeRestoreResponseFromJson(json);

  final bool success;
  final String message;
  final Data213 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidChargesChargeRestoreResponseToJson(this);
}
