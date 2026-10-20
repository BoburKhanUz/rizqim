// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data17.dart';

part 'get_v1_locations_response.g.dart';

@JsonSerializable()
class GetV1LocationsResponse {
  const GetV1LocationsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1LocationsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1LocationsResponseFromJson(json);

  final bool success;
  final String message;
  final Data17 data;

  Map<String, Object?> toJson() => _$GetV1LocationsResponseToJson(this);
}
