// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'skipped.g.dart';

@JsonSerializable()
class Skipped {
  const Skipped({
    required this.line,
    required this.opId,
    required this.seq,
    required this.device,
    required this.reason,
    required this.message,
  });

  factory Skipped.fromJson(Map<String, Object?> json) =>
      _$SkippedFromJson(json);

  final int line;
  @JsonKey(name: 'op_id')
  final String? opId;
  final int? seq;
  final String? device;
  final String reason;
  final String message;

  Map<String, Object?> toJson() => _$SkippedToJson(this);
}
