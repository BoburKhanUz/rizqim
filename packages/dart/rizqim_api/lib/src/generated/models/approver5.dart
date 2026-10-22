// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'approver5.g.dart';

@JsonSerializable()
class Approver5 {
  const Approver5({required this.id, required this.name});

  factory Approver5.fromJson(Map<String, Object?> json) =>
      _$Approver5FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Approver5ToJson(this);
}
