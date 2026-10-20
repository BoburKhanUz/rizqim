// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sold_out.g.dart';

@JsonSerializable()
class SoldOut {
  const SoldOut({required this.variants});

  factory SoldOut.fromJson(Map<String, Object?> json) =>
      _$SoldOutFromJson(json);

  final List<int> variants;

  Map<String, Object?> toJson() => _$SoldOutToJson(this);
}
