// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sold_by5.g.dart';

@JsonSerializable()
class SoldBy5 {
  const SoldBy5({required this.id, required this.name});

  factory SoldBy5.fromJson(Map<String, Object?> json) =>
      _$SoldBy5FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$SoldBy5ToJson(this);
}
