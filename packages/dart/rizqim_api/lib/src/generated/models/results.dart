// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'results.g.dart';

@JsonSerializable()
class Results {
  const Results({
    required this.opId,
    required this.seq,
    required this.type,
    required this.status,
    required this.reason,
    required this.message,
    required this.duplicate,
    required this.refs,
  });

  factory Results.fromJson(Map<String, Object?> json) =>
      _$ResultsFromJson(json);

  @JsonKey(name: 'op_id')
  final String opId;
  final int seq;
  final String type;
  final String status;
  final String? reason;
  final String? message;
  final bool duplicate;
  final Map<String, dynamic> refs;

  Map<String, Object?> toJson() => _$ResultsToJson(this);
}
