// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'delivery3.g.dart';

@JsonSerializable()
class Delivery3 {
  const Delivery3({this.name, this.mxik, this.packageCode, this.unit});

  factory Delivery3.fromJson(Map<String, Object?> json) =>
      _$Delivery3FromJson(json);

  final String? name;
  final String? mxik;
  @JsonKey(name: 'package_code')
  final String? packageCode;
  final String? unit;

  Map<String, Object?> toJson() => _$Delivery3ToJson(this);
}
