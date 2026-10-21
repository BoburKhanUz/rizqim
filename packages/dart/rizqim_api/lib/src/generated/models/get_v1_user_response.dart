// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data96.dart';

part 'get_v1_user_response.g.dart';

@JsonSerializable()
class GetV1UserResponse {
  const GetV1UserResponse({required this.success, required this.data});

  factory GetV1UserResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1UserResponseFromJson(json);

  final bool success;
  final Data96 data;

  Map<String, Object?> toJson() => _$GetV1UserResponseToJson(this);
}
