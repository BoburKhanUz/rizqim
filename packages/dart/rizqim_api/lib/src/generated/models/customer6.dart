// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'customer6.g.dart';

@JsonSerializable()
class Customer6 {
  const Customer6({required this.name, required this.phone});

  factory Customer6.fromJson(Map<String, Object?> json) =>
      _$Customer6FromJson(json);

  final String? name;
  final String? phone;

  Map<String, Object?> toJson() => _$Customer6ToJson(this);
}
