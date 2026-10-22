// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_guests_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidGuestsRequestBody {
  const V1PosOrdersUuidGuestsRequestBody({
    required this.version,
    required this.guestsCount,
    this.approvalToken,
  });

  factory V1PosOrdersUuidGuestsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidGuestsRequestBodyFromJson(json);

  final int version;
  @JsonKey(name: 'guests_count')
  final int guestsCount;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidGuestsRequestBodyToJson(this);
}
