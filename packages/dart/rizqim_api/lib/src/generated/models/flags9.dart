// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'flags9.g.dart';

@JsonSerializable()
class Flags9 {
  const Flags9({
    required this.flag,
    required this.opId,
    required this.amount,
    required this.name,
  });

  factory Flags9.fromJson(Map<String, Object?> json) => _$Flags9FromJson(json);

  final String flag;
  @JsonKey(name: 'op_id')
  final String? opId;
  final String? amount;
  final String? name;

  Map<String, Object?> toJson() => _$Flags9ToJson(this);
}
