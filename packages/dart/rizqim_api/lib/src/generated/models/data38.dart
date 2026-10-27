// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data38.g.dart';

@JsonSerializable()
class Data38 {
  const Data38({required this.address, required this.lat, required this.lng});

  factory Data38.fromJson(Map<String, Object?> json) => _$Data38FromJson(json);

  final String address;
  final num lat;
  final num lng;

  Map<String, Object?> toJson() => _$Data38ToJson(this);
}
