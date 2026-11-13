// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'flags25.g.dart';

@JsonSerializable()
class Flags25 {
  const Flags25({
    required this.flag,
    required this.opId,
    required this.amount,
    required this.name,
  });

  factory Flags25.fromJson(Map<String, Object?> json) =>
      _$Flags25FromJson(json);

  final String flag;
  @JsonKey(name: 'op_id')
  final String? opId;
  final String? amount;
  final String? name;

  Map<String, Object?> toJson() => _$Flags25ToJson(this);
}
