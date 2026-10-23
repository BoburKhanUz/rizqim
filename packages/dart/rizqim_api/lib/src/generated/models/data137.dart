// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel4.dart';
import 'branch12.dart';
import 'category.dart';
import 'products.dart';
import 'availability.dart';

part 'data137.g.dart';

@JsonSerializable()
class Data137 {
  const Data137({
    required this.channel,
    required this.menuVersion,
    required this.branch,
    required this.categories,
    required this.products,
    required this.availability,
  });

  factory Data137.fromJson(Map<String, Object?> json) =>
      _$Data137FromJson(json);

  final Channel4 channel;
  @JsonKey(name: 'menu_version')
  final String menuVersion;
  final Branch12 branch;
  final List<Category> categories;
  final List<Products> products;
  final Availability availability;

  Map<String, Object?> toJson() => _$Data137ToJson(this);
}
