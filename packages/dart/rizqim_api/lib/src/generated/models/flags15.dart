// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'flags15.g.dart';

@JsonSerializable()
class Flags15 {
  const Flags15({
    required this.flag,
    required this.opId,
    required this.amount,
    required this.name,
  });

  factory Flags15.fromJson(Map<String, Object?> json) =>
      _$Flags15FromJson(json);

  final String flag;
  @JsonKey(name: 'op_id')
  final String? opId;
  final String? amount;
  final String? name;

  Map<String, Object?> toJson() => _$Flags15ToJson(this);
}
