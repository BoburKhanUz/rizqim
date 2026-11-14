// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'kazans.g.dart';

@JsonSerializable()
class Kazans {
  const Kazans({required this.sizeKg, required this.count});

  factory Kazans.fromJson(Map<String, Object?> json) => _$KazansFromJson(json);

  @JsonKey(name: 'size_kg')
  final int sizeKg;
  final int count;

  Map<String, Object?> toJson() => _$KazansToJson(this);
}
