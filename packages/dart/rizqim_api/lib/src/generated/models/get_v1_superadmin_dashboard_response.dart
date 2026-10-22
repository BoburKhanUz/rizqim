// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data184.dart';

part 'get_v1_superadmin_dashboard_response.g.dart';

@JsonSerializable()
class GetV1SuperadminDashboardResponse {
  const GetV1SuperadminDashboardResponse({
    required this.success,
    required this.data,
  });

  factory GetV1SuperadminDashboardResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1SuperadminDashboardResponseFromJson(json);

  final bool success;
  final Data184 data;

  Map<String, Object?> toJson() =>
      _$GetV1SuperadminDashboardResponseToJson(this);
}
