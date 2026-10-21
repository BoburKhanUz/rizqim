// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'customer3.g.dart';

@JsonSerializable()
class Customer3 {
  const Customer3({required this.name, required this.phone});

  factory Customer3.fromJson(Map<String, Object?> json) =>
      _$Customer3FromJson(json);

  final String? name;
  final String? phone;

  Map<String, Object?> toJson() => _$Customer3ToJson(this);
}
