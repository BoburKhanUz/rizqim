// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'employee.g.dart';

@JsonSerializable()
class Employee {
  const Employee({required this.id, required this.name});

  factory Employee.fromJson(Map<String, Object?> json) =>
      _$EmployeeFromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$EmployeeToJson(this);
}
