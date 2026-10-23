// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'flags12.g.dart';

@JsonSerializable()
class Flags12 {
  const Flags12({
    required this.flag,
    required this.opId,
    required this.amount,
    required this.name,
  });

  factory Flags12.fromJson(Map<String, Object?> json) =>
      _$Flags12FromJson(json);

  final String flag;
  @JsonKey(name: 'op_id')
  final String? opId;
  final String? amount;
  final String? name;

  Map<String, Object?> toJson() => _$Flags12ToJson(this);
}
