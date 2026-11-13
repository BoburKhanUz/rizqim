// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data243.dart';

part 'get_v1_pos_lan_response.g.dart';

@JsonSerializable()
class GetV1PosLanResponse {
  const GetV1PosLanResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosLanResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosLanResponseFromJson(json);

  final bool success;
  final String message;
  final Data243? data;

  Map<String, Object?> toJson() => _$GetV1PosLanResponseToJson(this);
}
