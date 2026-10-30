// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data146.dart';

part 'get_v1_owner_reports_sales_response.g.dart';

@JsonSerializable()
class GetV1OwnerReportsSalesResponse {
  const GetV1OwnerReportsSalesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerReportsSalesResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerReportsSalesResponseFromJson(json);

  final bool success;
  final String message;
  final Data146 data;

  Map<String, Object?> toJson() => _$GetV1OwnerReportsSalesResponseToJson(this);
}
