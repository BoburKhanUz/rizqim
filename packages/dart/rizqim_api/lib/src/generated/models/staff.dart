// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'staff.g.dart';

@JsonSerializable()
class Staff {
  const Staff({required this.id, required this.name, required this.role});

  factory Staff.fromJson(Map<String, Object?> json) => _$StaffFromJson(json);

  final int id;
  final String name;
  final String role;

  Map<String, Object?> toJson() => _$StaffToJson(this);
}
