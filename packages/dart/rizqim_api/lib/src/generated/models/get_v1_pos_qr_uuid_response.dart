// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data329.dart';

part 'get_v1_pos_qr_uuid_response.g.dart';

@JsonSerializable()
class GetV1PosQrUuidResponse {
  const GetV1PosQrUuidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosQrUuidResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosQrUuidResponseFromJson(json);

  final bool success;
  final String message;
  final Data329 data;

  Map<String, Object?> toJson() => _$GetV1PosQrUuidResponseToJson(this);
}
