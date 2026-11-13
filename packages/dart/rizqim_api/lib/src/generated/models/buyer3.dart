// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'buyer3.g.dart';

@JsonSerializable()
class Buyer3 {
  const Buyer3({required this.name, required this.phone});

  factory Buyer3.fromJson(Map<String, Object?> json) => _$Buyer3FromJson(json);

  final String? name;
  final String? phone;

  Map<String, Object?> toJson() => _$Buyer3ToJson(this);
}
