// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data331.dart';

part 'post_v1_pos_qr_uuid_simulate_paid_response.g.dart';

@JsonSerializable()
class PostV1PosQrUuidSimulatePaidResponse {
  const PostV1PosQrUuidSimulatePaidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosQrUuidSimulatePaidResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosQrUuidSimulatePaidResponseFromJson(json);

  final bool success;
  final String message;
  final Data331 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosQrUuidSimulatePaidResponseToJson(this);
}
