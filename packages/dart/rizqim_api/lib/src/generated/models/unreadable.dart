// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'unreadable.g.dart';

@JsonSerializable()
class Unreadable {
  const Unreadable({
    required this.index,
    required this.opId,
    required this.seq,
    required this.reason,
    required this.message,
  });

  factory Unreadable.fromJson(Map<String, Object?> json) =>
      _$UnreadableFromJson(json);

  final int index;
  @JsonKey(name: 'op_id')
  final String? opId;
  final int? seq;
  final String reason;
  final String message;

  Map<String, Object?> toJson() => _$UnreadableToJson(this);
}
