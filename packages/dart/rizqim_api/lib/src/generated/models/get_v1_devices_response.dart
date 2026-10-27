// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data30.dart';

part 'get_v1_devices_response.g.dart';

@JsonSerializable()
class GetV1DevicesResponse {
  const GetV1DevicesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1DevicesResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1DevicesResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data30> data;

  Map<String, Object?> toJson() => _$GetV1DevicesResponseToJson(this);
}
