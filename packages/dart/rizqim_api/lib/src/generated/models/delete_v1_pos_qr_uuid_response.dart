// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data330.dart';

part 'delete_v1_pos_qr_uuid_response.g.dart';

@JsonSerializable()
class DeleteV1PosQrUuidResponse {
  const DeleteV1PosQrUuidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1PosQrUuidResponse.fromJson(Map<String, Object?> json) =>
      _$DeleteV1PosQrUuidResponseFromJson(json);

  final bool success;
  final String message;
  final Data330 data;

  Map<String, Object?> toJson() => _$DeleteV1PosQrUuidResponseToJson(this);
}
