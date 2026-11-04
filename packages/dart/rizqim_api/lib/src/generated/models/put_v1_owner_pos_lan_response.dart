// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data130.dart';

part 'put_v1_owner_pos_lan_response.g.dart';

@JsonSerializable()
class PutV1OwnerPosLanResponse {
  const PutV1OwnerPosLanResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerPosLanResponse.fromJson(Map<String, Object?> json) =>
      _$PutV1OwnerPosLanResponseFromJson(json);

  final bool success;
  final String message;
  final Data130 data;

  Map<String, Object?> toJson() => _$PutV1OwnerPosLanResponseToJson(this);
}
