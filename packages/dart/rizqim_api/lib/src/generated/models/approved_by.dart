// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'approved_by.g.dart';

@JsonSerializable()
class ApprovedBy {
  const ApprovedBy({required this.id, required this.name, required this.role});

  factory ApprovedBy.fromJson(Map<String, Object?> json) =>
      _$ApprovedByFromJson(json);

  final int id;
  final String name;
  final String role;

  Map<String, Object?> toJson() => _$ApprovedByToJson(this);
}
