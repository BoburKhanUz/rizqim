// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'unsigned_tills2.dart';

part 'data160.g.dart';

@JsonSerializable()
class Data160 {
  const Data160({
    required this.enforce,
    required this.signingTills,
    required this.unsignedTills,
  });

  factory Data160.fromJson(Map<String, Object?> json) =>
      _$Data160FromJson(json);

  final bool enforce;
  @JsonKey(name: 'signing_tills')
  final int signingTills;
  @JsonKey(name: 'unsigned_tills')
  final List<UnsignedTills2> unsignedTills;

  Map<String, Object?> toJson() => _$Data160ToJson(this);
}
