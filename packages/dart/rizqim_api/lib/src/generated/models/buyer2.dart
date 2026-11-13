// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'buyer2.g.dart';

@JsonSerializable()
class Buyer2 {
  const Buyer2({required this.name, required this.phone});

  factory Buyer2.fromJson(Map<String, Object?> json) => _$Buyer2FromJson(json);

  final String? name;
  final String? phone;

  Map<String, Object?> toJson() => _$Buyer2ToJson(this);
}
