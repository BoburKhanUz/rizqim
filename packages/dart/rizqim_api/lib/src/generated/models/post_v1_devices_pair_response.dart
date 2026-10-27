// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data32.dart';

part 'post_v1_devices_pair_response.g.dart';

@JsonSerializable()
class PostV1DevicesPairResponse {
  const PostV1DevicesPairResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1DevicesPairResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1DevicesPairResponseFromJson(json);

  final bool success;
  final String message;
  final Data32 data;

  Map<String, Object?> toJson() => _$PostV1DevicesPairResponseToJson(this);
}
