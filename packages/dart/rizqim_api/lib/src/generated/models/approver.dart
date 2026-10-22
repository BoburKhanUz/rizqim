// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'approver.g.dart';

@JsonSerializable()
class Approver {
  const Approver({required this.id, required this.name});

  factory Approver.fromJson(Map<String, Object?> json) =>
      _$ApproverFromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$ApproverToJson(this);
}
