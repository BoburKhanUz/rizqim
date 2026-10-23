// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'flags6.g.dart';

@JsonSerializable()
class Flags6 {
  const Flags6({
    required this.flag,
    required this.opId,
    required this.amount,
    required this.name,
  });

  factory Flags6.fromJson(Map<String, Object?> json) => _$Flags6FromJson(json);

  final String flag;
  @JsonKey(name: 'op_id')
  final String? opId;
  final String? amount;
  final String? name;

  Map<String, Object?> toJson() => _$Flags6ToJson(this);
}
