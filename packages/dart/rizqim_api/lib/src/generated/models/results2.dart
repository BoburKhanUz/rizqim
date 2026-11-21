// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'results2.g.dart';

@JsonSerializable()
class Results2 {
  const Results2({
    required this.opId,
    required this.seq,
    required this.type,
    required this.status,
    required this.reason,
    required this.message,
    required this.duplicate,
    required this.sigStatus,
    required this.refs,
  });

  factory Results2.fromJson(Map<String, Object?> json) =>
      _$Results2FromJson(json);

  @JsonKey(name: 'op_id')
  final String opId;
  final int seq;
  final String type;
  final String status;
  final String? reason;
  final String? message;
  final bool duplicate;
  @JsonKey(name: 'sig_status')
  final String? sigStatus;
  final Map<String, dynamic> refs;

  Map<String, Object?> toJson() => _$Results2ToJson(this);
}
