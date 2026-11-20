// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff10.dart';

part 'data243.g.dart';

@JsonSerializable()
class Data243 {
  const Data243({required this.staff});

  factory Data243.fromJson(Map<String, Object?> json) =>
      _$Data243FromJson(json);

  final List<Staff10> staff;

  Map<String, Object?> toJson() => _$Data243ToJson(this);
}
