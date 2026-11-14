// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'user33.g.dart';

@JsonSerializable()
class User33 {
  const User33({required this.id, required this.name});

  factory User33.fromJson(Map<String, Object?> json) => _$User33FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$User33ToJson(this);
}
