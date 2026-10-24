// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items40.dart';
import 'fire_mode.dart';

part 'v1_pos_orders_uuid_rounds_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidRoundsRequestBody {
  const V1PosOrdersUuidRoundsRequestBody({
    required this.version,
    required this.items,
    this.fireMode,
    this.send,
    this.approvalToken,
  });

  factory V1PosOrdersUuidRoundsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidRoundsRequestBodyFromJson(json);

  final int version;
  final List<Items40> items;
  @JsonKey(name: 'fire_mode')
  final FireMode? fireMode;
  final bool? send;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidRoundsRequestBodyToJson(this);
}
