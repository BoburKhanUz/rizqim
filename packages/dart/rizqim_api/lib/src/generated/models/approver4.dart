// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'approver4.g.dart';

@JsonSerializable()
class Approver4 {
  const Approver4({required this.id, required this.name});

  factory Approver4.fromJson(Map<String, Object?> json) =>
      _$Approver4FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Approver4ToJson(this);
}
