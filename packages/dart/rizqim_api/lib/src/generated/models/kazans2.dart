// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'kazans2.g.dart';

@JsonSerializable()
class Kazans2 {
  const Kazans2({required this.sizeKg, required this.count});

  factory Kazans2.fromJson(Map<String, Object?> json) =>
      _$Kazans2FromJson(json);

  @JsonKey(name: 'size_kg')
  final int sizeKg;
  final int count;

  Map<String, Object?> toJson() => _$Kazans2ToJson(this);
}
