// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data158.dart';

part 'get_v1_pos_bootstrap_response.g.dart';

@JsonSerializable()
class GetV1PosBootstrapResponse {
  const GetV1PosBootstrapResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosBootstrapResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosBootstrapResponseFromJson(json);

  final bool success;
  final String message;
  final Data158 data;

  Map<String, Object?> toJson() => _$GetV1PosBootstrapResponseToJson(this);
}
