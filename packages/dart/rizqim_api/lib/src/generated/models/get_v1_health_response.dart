// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'status.dart';
import 'checks.dart';

part 'get_v1_health_response.g.dart';

@JsonSerializable()
class GetV1HealthResponse {
  const GetV1HealthResponse({required this.status, required this.checks});

  factory GetV1HealthResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1HealthResponseFromJson(json);

  final Status status;
  final Map<String, Checks> checks;

  Map<String, Object?> toJson() => _$GetV1HealthResponseToJson(this);
}
