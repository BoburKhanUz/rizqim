// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_status.g.dart';

@JsonSerializable()
class ByStatus {
  const ByStatus({required this.status, required this.count});

  factory ByStatus.fromJson(Map<String, Object?> json) =>
      _$ByStatusFromJson(json);

  final String status;
  final int count;

  Map<String, Object?> toJson() => _$ByStatusToJson(this);
}
