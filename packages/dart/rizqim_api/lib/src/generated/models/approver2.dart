// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'approver2.g.dart';

@JsonSerializable()
class Approver2 {
  const Approver2({required this.id, required this.name});

  factory Approver2.fromJson(Map<String, Object?> json) =>
      _$Approver2FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Approver2ToJson(this);
}
