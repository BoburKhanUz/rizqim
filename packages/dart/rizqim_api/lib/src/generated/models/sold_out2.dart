// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sold_out2.g.dart';

@JsonSerializable()
class SoldOut2 {
  const SoldOut2({required this.variants});

  factory SoldOut2.fromJson(Map<String, Object?> json) =>
      _$SoldOut2FromJson(json);

  final List<int> variants;

  Map<String, Object?> toJson() => _$SoldOut2ToJson(this);
}
